/*
 * jQuery File Upload Plugin 4.5.1
 * https://github.com/blueimp/jQuery-File-Upload
 *
 * Copyright 2010, Sebastian Tschan
 * https://blueimp.net
 *
 * Licensed under the MIT license:
 * http://creativecommons.org/licenses/MIT/
 */

/*jslint browser: true, unparam: true */
/*global XMLHttpRequestUpload, File, FileReader, FormData, ProgressEvent, unescape, jQuery, upload */

(function ($) {
    'use strict';

    var defaultNamespace = 'file_upload',
        undef = 'undefined',
        func = 'function',
        FileUpload,
        methods,

        MultiLoader = function (callBack, numOrList) {
            var loaded = 0,
                list = [];
            if (numOrList) {
                if (numOrList.length) {
                    list = numOrList;
                } else {
                    list[numOrList - 1] = null;
                }
            }
            this.complete = function () {
                loaded += 1;
                if (loaded === list.length) {
                    callBack(list);
                    loaded = 0;
                    list = [];
                }
            };
            this.push = function (item) {
                list.push(item);
            };
            this.getList = function () {
                return list;
            };
        },
        
        SequenceHandler = function () {
            var sequence = [];
            this.push = function (callBack) {
                sequence.push(callBack);
                if (sequence.length === 1) {
                    callBack();
                }
            };
            this.next = function () {
                sequence.shift();
                if (sequence.length) {
                    sequence[0]();
                }
            };
        };
        
    FileUpload = function (container) {
        var fileUpload = this,
            uploadForm,
            fileInput,
            settings = {
                namespace: defaultNamespace,
                uploadFormFilter: function (index) {
                    return true;
                },
                fileInputFilter: function (index) {
                    return true;
                },
                cssClass: defaultNamespace,
                dragDropSupport: true,
                dropZone: container,
                url: function (form) {
                    return form.attr('action');
                },
                method: function (form) {
                    return form.attr('method');
                },
                fieldName: function (input) {
                    return input.attr('name');
                },
                formData: function (form) {
                    return form.serializeArray();
                },
                requestHeaders: null,
                multipart: true,
                multiFileRequest: false,
                withCredentials: false,
                forceIframeUpload: false,
                sequentialUploads: false,
                maxChunkSize: null,
                maxFileReaderSize: 50000000,
                replaceFileInput: true
            },
            documentListeners = {},
            dropZoneListeners = {},
            protocolRegExp = /^http(s)?:\/\//,
            optionsReference,
            multiLoader = new MultiLoader(function (list) {
                if (typeof settings.onLoadAll === func) {
                    settings.onLoadAll(list);
                }
            }),
            sequenceHandler = new SequenceHandler(),
            
            completeNext = function () {
                multiLoader.complete();
                sequenceHandler.next();
            },

            isXHRUploadCapable = function () {
                return typeof XMLHttpRequest !== undef && typeof XMLHttpRequestUpload !== undef &&
                    typeof File !== undef && (!settings.multipart || typeof FormData !== undef ||
                    (typeof FileReader !== undef && typeof XMLHttpRequest.prototype.sendAsBinary === func));
            },

            initEventHandlers = function () {
                if (settings.dragDropSupport) {
                    if (typeof settings.onDocumentDragEnter === func) {
                        documentListeners['dragenter.' + settings.namespace] = function (e) {
                            settings.onDocumentDragEnter(e);
                        };
                    }
                    if (typeof settings.onDocumentDragLeave === func) {
                        documentListeners['dragleave.' + settings.namespace] = function (e) {
                            settings.onDocumentDragLeave(e);
                        };
                    }
                    documentListeners['dragover.'   + settings.namespace] = fileUpload.onDocumentDragOver;
                    documentListeners['drop.'       + settings.namespace] = fileUpload.onDocumentDrop;
                    $(document).bind(documentListeners);
                    if (typeof settings.onDragEnter === func) {
                        dropZoneListeners['dragenter.' + settings.namespace] = function (e) {
                            settings.onDragEnter(e);
                        };
                    }
                    if (typeof settings.onDragLeave === func) {
                        dropZoneListeners['dragleave.' + settings.namespace] = function (e) {
                            settings.onDragLeave(e);
                        };
                    }
                    dropZoneListeners['dragover.'   + settings.namespace] = fileUpload.onDragOver;
                    dropZoneListeners['drop.'       + settings.namespace] = fileUpload.onDrop;
                    settings.dropZone.bind(dropZoneListeners);
                }
                fileInput.bind('change.' + settings.namespace, fileUpload.onChange);
            },

            removeEventHandlers = function () {
                $.each(documentListeners, function (key, value) {
                    $(document).unbind(key, value);
                });
                $.each(dropZoneListeners, function (key, value) {
                    settings.dropZone.unbind(key, value);
                });
                fileInput.unbind('change.' + settings.namespace);
            },

            isChunkedUpload = function (settings) {
                return typeof settings.uploadedBytes !== undef;
            },

            createProgressEvent = function (lengthComputable, loaded, total) {
                var event;
                if (typeof document.createEvent === func && typeof ProgressEvent !== undef) {
                    event = document.createEvent('ProgressEvent');
                    event.initProgressEvent(
                        'progress',
                        false,
                        false,
                        lengthComputable,
                        loaded,
                        total
                    );
                } else {
                    event = {
                        lengthComputable: true,
                        loaded: loaded,
                        total: total
                    };
                }
                return event;
            },

            getProgressTotal = function (files, index, settings) {
                var i,
                    total;
                if (typeof settings.progressTotal === undef) {
                    if (files[index]) {
                        total = files[index].size;
                        settings.progressTotal = total ? total : 1;
                    } else {
                        total = 0;
                        for (i = 0; i < files.length; i += 1) {
                            total += files[i].size;
                        }
                        settings.progressTotal = total;
                    }
                }
                return settings.progressTotal;
            },

            handleGlobalProgress = function (event, files, index, xhr, settings) {
                var progressEvent,
                    loaderList,
                    globalLoaded = 0,
                    globalTotal = 0;
                if (event.lengthComputable && typeof settings.onProgressAll === func) {
                    settings.progressLoaded = parseInt(
                        event.loaded / event.total * getProgressTotal(files, index, settings),
                        10
                    );
                    loaderList = multiLoader.getList();
                    $.each(loaderList, function (index, item) {
                        // item is an array with [files, index, xhr, settings]
                        globalLoaded += item[3].progressLoaded || 0;
                        globalTotal += getProgressTotal(item[0], item[1], item[3]);
                    });
                    progressEvent = createProgressEvent(
                        true,
                        globalLoaded,
                        globalTotal
                    );
                    settings.onProgressAll(progressEvent, loaderList);
                }
            },
            
            handleLoadEvent = function (event, files, index, xhr, settings) {
                var progressEvent;
                if (isChunkedUpload(settings)) {
                    settings.uploadedBytes += settings.chunkSize;
                    progressEvent = createProgressEvent(
                        true,
                        settings.uploadedBytes,
                        files[index].size
                    );
                    if (typeof settings.onProgress === func) {
                        settings.onProgress(progressEvent, files, index, xhr, settings);
                    }
                    handleGlobalProgress(progressEvent, files, index, xhr, settings);
                    if (settings.uploadedBytes < files[index].size) {
                        if (typeof settings.resumeUpload === func) {
                            settings.resumeUpload(
                                event,
                                files,
                                index,
                                xhr,
                                settings,
                                function () {
                                    upload(event, files, index, xhr, settings, true);
                                }
                            );
                        } else {
                            upload(event, files, index, xhr, settings, true);
                        }
                        return;
                    }
                }
                settings.progressLoaded = getProgressTotal(files, index, settings);
                if (typeof settings.onLoad === func) {
                    settings.onLoad(event, files, index, xhr, settings);
                }
                completeNext();
            },
            
            handleProgressEvent = function (event, files, index, xhr, settings) {
                var progressEvent = event;
                if (isChunkedUpload(settings) && event.lengthComputable) {
                    progressEvent = createProgressEvent(
                        true,
                        settings.uploadedBytes + parseInt(event.loaded / event.total * settings.chunkSize, 10),
                        files[index].size
                    );
                }
                if (typeof settings.onProgress === func) {
                    settings.onProgress(progressEvent, files, index, xhr, settings);
                }
                handleGlobalProgress(progressEvent, files, index, xhr, settings);
            },
            
            initUploadEventHandlers = function (files, index, xhr, settings) {
                if (xhr.upload) {
                    xhr.upload.onprogress = function (e) {
                        handleProgressEvent(e, files, index, xhr, settings);
                    };
                }
                xhr.onload = function (e) {
                    handleLoadEvent(e, files, index, xhr, settings);
                };
                xhr.onabort = function (e) {
                    settings.progressTotal = settings.progressLoaded;
                    if (typeof settings.onAbort === func) {
                        settings.onAbort(e, files, index, xhr, settings);
                    }
                    completeNext();
                };
                xhr.onerror = function (e) {
                    settings.progressTotal = settings.progressLoaded;
                    if (typeof settings.onError === func) {
                        settings.onError(e, files, index, xhr, settings);
                    }
                    completeNext();
                };
            },

            getUrl = function (settings) {
                if (typeof settings.url === func) {
                    return settings.url(settings.uploadForm || uploadForm);
                }
                return settings.url;
            },
            
            getMethod = function (settings) {
                if (typeof settings.method === func) {
                    return settings.method(settings.uploadForm || uploadForm);
                }
                return settings.method;
            },
            
            getFieldName = function (settings) {
                if (typeof settings.fieldName === func) {
                    return settings.fieldName(settings.fileInput || fileInput);
                }
                return settings.fieldName;
            },

            getFormData = function (settings) {
                var formData;
                if (typeof settings.formData === func) {
                    return settings.formData(settings.uploadForm || uploadForm);
                } else if ($.isArray(settings.formData)) {
                    return settings.formData;
                } else if (settings.formData) {
                    formData = [];
                    $.each(settings.formData, function (name, value) {
                        formData.push({name: name, value: value});
                    });
                    return formData;
                }
                return [];
            },

            isSameDomain = function (url) {
                if (protocolRegExp.test(url)) {
                    var host = location.host,
                        indexStart = location.protocol.length + 2,
                        index = url.indexOf(host, indexStart),
                        pathIndex = index + host.length;
                    if ((index === indexStart || index === url.indexOf('@', indexStart) + 1) &&
                            (url.length === pathIndex || $.inArray(url.charAt(pathIndex), ['/', '?', '#']) !== -1)) {
                        return true;
                    }
                    return false;
                }
                return true;
            },

            initUploadRequest = function (files, index, xhr, settings) {
                var file = files[index],
                    url = getUrl(settings),
                    sameDomain = isSameDomain(url);
                xhr.open(getMethod(settings), url, true);
                if (sameDomain) {
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    if (!settings.multipart || isChunkedUpload(settings)) {
                        xhr.setRequestHeader('X-File-Name', file.name);
                        xhr.setRequestHeader('X-File-Type', file.type);
                        xhr.setRequestHeader('X-File-Size', file.size);
                        if (!isChunkedUpload(settings)) {
                            xhr.setRequestHeader('Content-Type', file.type);
                        } else if (!settings.multipart) {
                            xhr.setRequestHeader('Content-Type', 'application/octet-stream');
                        }
                    }
                } else if (settings.withCredentials) {
                    xhr.withCredentials = true;
                }
                if ($.isArray(settings.requestHeaders)) {
                    $.each(settings.requestHeaders, function (index, header) {
                        xhr.setRequestHeader(header.name, header.value);
                    });
                } else if (settings.requestHeaders) {
                    $.each(settings.requestHeaders, function (name, value) {
                        xhr.setRequestHeader(name, value);
                    });
                }
            },

            formDataUpload = function (files, xhr, settings) {
                var formData = new FormData(),
                    i;
                $.each(getFormData(settings), function (index, field) {
                    formData.append(field.name, field.value);
                });
                for (i = 0; i < files.length; i += 1) {
                    formData.append(getFieldName(settings), files[i]);
                }
                xhr.send(formData);
            },

            loadFileContent = function (file, callBack) {
                file.reader = new FileReader();
                file.reader.onload = callBack;
                file.reader.readAsBinaryString(file);
            },

            utf8encode = function (str) {
                return unescape(encodeURIComponent(str));
            },

            buildMultiPartFormData = function (boundary, files, filesFieldName, fields) {
                var doubleDash = '--',
                    crlf     = '\r\n',
                    formData = '',
                    buffer = [];
                $.each(fields, function (index, field) {
                    formData += doubleDash + boundary + crlf +
                        'Content-Disposition: form-data; name="' +
                        utf8encode(field.name) +
                        '"' + crlf + crlf +
                        utf8encode(field.value) + crlf;
                });
                $.each(files, function (index, file) {
                    formData += doubleDash + boundary + crlf +
                        'Content-Disposition: form-data; name="' +
                        utf8encode(filesFieldName) +
                        '"; filename="' + utf8encode(file.name) + '"' + crlf +
                        'Content-Type: ' + utf8encode(file.type) + crlf + crlf;
                    buffer.push(formData);
                    buffer.push(file.reader.result);
                    delete file.reader;
                    formData = crlf;
                });
                formData += doubleDash + boundary + doubleDash + crlf;
                buffer.push(formData);
                return buffer.join('');
            },
            
            fileReaderUpload = function (files, xhr, settings) {
                var boundary = '----MultiPartFormBoundary' + (new Date()).getTime(),
                    loader,
                    i;
                xhr.setRequestHeader('Content-Type', 'multipart/form-data; boundary=' + boundary);
                loader = new MultiLoader(function () {
                    xhr.sendAsBinary(buildMultiPartFormData(
                        boundary,
                        files,
                        getFieldName(settings),
                        getFormData(settings)
                    ));
                }, files.length);
                for (i = 0; i < files.length; i += 1) {
                    loadFileContent(files[i], loader.complete);
                }
            },

            getBlob = function (file, settings) {
                var blob,
                    ub = settings.uploadedBytes,
                    mcs = settings.maxChunkSize;
                if (file && typeof file.slice === func && (ub || (mcs && mcs < file.size))) {
                    settings.uploadedBytes = ub = ub || 0;
                    blob = file.slice(ub, mcs || file.size - ub);
                    settings.chunkSize = blob.size;
                    return blob;
                }
                return file;
            },

            upload = function (event, files, index, xhr, settings, nextChunk) {
                var send;
                send = function () {
                    if (!nextChunk) {
                        if (typeof settings.onSend === func &&
                                settings.onSend(event, files, index, xhr, settings) === false) {
                            completeNext();
                            return;
                        }
                    }
                    var blob = getBlob(files[index], settings),
                        filesToUpload;
                    initUploadEventHandlers(files, index, xhr, settings);
                    initUploadRequest(files, index, xhr, settings);
                    if (!settings.multipart) {
                        if (xhr.upload) {
                            xhr.send(blob);
                        } else {
                            $.error('Browser does not support XHR file uploads');
                        }
                    } else {
                        filesToUpload = (typeof index === 'number') ? [blob] : files;
                        if (typeof FormData !== undef) {
                            formDataUpload(filesToUpload, xhr, settings);
                        } else if (typeof FileReader !== undef && typeof xhr.sendAsBinary === func) {
                            fileReaderUpload(filesToUpload, xhr, settings);
                        } else {
                            $.error('Browser does not support multipart/form-data XHR file uploads');
                        }
                    }
                };
                if (!nextChunk) {
                    multiLoader.push(Array.prototype.slice.call(arguments, 1));
                    if (settings.sequentialUploads) {
                        sequenceHandler.push(send);
                        return;
                    }
                }
                send();
            },

            handleUpload = function (event, files, input, form, index) {
                var xhr = new XMLHttpRequest(),
                    uploadSettings = $.extend({}, settings);
                uploadSettings.fileInput = input;
                uploadSettings.uploadForm = form;
                if (typeof uploadSettings.initUpload === func) {
                    uploadSettings.initUpload(
                        event,
                        files,
                        index,
                        xhr,
                        uploadSettings,
                        function () {
                            upload(event, files, index, xhr, uploadSettings);
                        }
                    );
                } else {
                    upload(event, files, index, xhr, uploadSettings);
                }
            },

            handleLegacyGlobalProgress = function (event, files, index, iframe, settings) {
                var total = 0,
                    progressEvent;
                if (typeof index === undef) {
                    $.each(files, function (index, file) {
                        total += file.size ? file.size : 1;
                    });
                } else {
                    total = files[index].size ? files[index].size : 1;
                }
                progressEvent = createProgressEvent(true, total, total);
                settings.progressLoaded = total;
                handleGlobalProgress(progressEvent, files, index, iframe, settings);
            },

            legacyUploadFormDataInit = function (input, form, settings) {
                var formData = getFormData(settings);
                form.find(':input').not(':disabled')
                    .attr('disabled', true)
                    .addClass(settings.namespace + '_disabled');
                $.each(formData, function (index, field) {
                    $('<input type="hidden"/>')
                        .attr('name', field.name)
                        .val(field.value)
                        .addClass(settings.namespace + '_form_data')
                        .appendTo(form);
                });
                input
                    .attr('name', getFieldName(settings))
                    .appendTo(form);
            },

            legacyUploadFormDataReset = function (input, form, settings) {
                input.detach();
                form.find('.' + settings.namespace + '_disabled')
                    .removeAttr('disabled')
                    .removeClass(settings.namespace + '_disabled');
                form.find('.' + settings.namespace + '_form_data').remove();
            },

            legacyUpload = function (event, files, input, form, iframe, settings, index) {
                var send;
                send = function () {
                    if (typeof settings.onSend === func && settings.onSend(event, files, index, iframe, settings) === false) {
                        completeNext();
                        return;
                    }
                    var originalAttributes = {
                        'action': form.attr('action'),
                        'method': form.attr('method'),
                        'target': form.attr('target'),
                        'enctype': form.attr('enctype')
                    };
                    iframe
                        .unbind('abort')
                        .bind('abort', function (e) {
                            iframe.readyState = 0;
                            // javascript:false as iframe src prevents warning popups on HTTPS in IE6
                            // concat is used here to prevent the "Script URL" JSLint error:
                            iframe.unbind('load').attr('src', 'javascript'.concat(':false;'));
                            handleLegacyGlobalProgress(e, files, index, iframe, settings);
                            if (typeof settings.onAbort === func) {
                                settings.onAbort(e, files, index, iframe, settings);
                            }
                            completeNext();
                        })
                        .unbind('load')
                        .bind('load', function (e) {
                            iframe.readyState = 4;
                            handleLegacyGlobalProgress(e, files, index, iframe, settings);
                            if (typeof settings.onLoad === func) {
                                settings.onLoad(e, files, index, iframe, settings);
                            }
                            // Fix for IE endless progress bar activity bug
                            // (happens on form submits to iframe targets):
                            $('<iframe src="javascript:false;" style="display:none;"></iframe>')
                                .appendTo(form).remove();
                            completeNext();
                        });
                    form
                        .attr('action', getUrl(settings))
                        .attr('method', getMethod(settings))
                        .attr('target', iframe.attr('name'))
                        .attr('enctype', 'multipart/form-data');
                    legacyUploadFormDataInit(input, form, settings);
                    iframe.readyState = 2;
                    form.get(0).submit();
                    legacyUploadFormDataReset(input, form, settings);
                    $.each(originalAttributes, function (name, value) {
                        if (value) {
                            form.attr(name, value);
                        } else {
                            form.removeAttr(name);
                        }
                    });
                };
                multiLoader.push([files, index, iframe, settings]);
                if (settings.sequentialUploads) {
                    sequenceHandler.push(send);
                } else {
                    send();
                }
            },

            normalizeFile = function (index, file) {
                if (typeof file.name === undef && typeof file.size === undef) {
                    file.name = file.fileName;
                    file.size = file.fileSize;
                }
            },

            handleLegacyUpload = function (event, input, form, index) {
                if (!(event && input && form)) {
                    $.error('Iframe based File Upload requires a file input change event');
                    return;
                }
                // javascript:false as iframe src prevents warning popups on HTTPS in IE6:
                var iframe = $('<iframe src="javascript:false;" style="display:none;" name="iframe_' +
                    settings.namespace + '_' + (new Date()).getTime() + '"></iframe>'),
                    uploadSettings = $.extend({}, settings),
                    files = event.target && event.target.files;
                files = files ? Array.prototype.slice.call(files, 0) : [{name: input.val(), type: null, size: null}];
                $.each(files, normalizeFile);
                index = files.length === 1 ? 0 : index;
                uploadSettings.fileInput = input;
                uploadSettings.uploadForm = form;
                iframe.readyState = 0;
                iframe.abort = function () {
                    iframe.trigger('abort');
                };
                iframe.bind('load', function () {
                    iframe.unbind('load');
                    if (typeof uploadSettings.initUpload === func) {
                        uploadSettings.initUpload(
                            event,
                            files,
                            index,
                            iframe,
                            uploadSettings,
                            function () {
                                legacyUpload(event, files, input, form, iframe, uploadSettings, index);
                            }
                        );
                    } else {
                        legacyUpload(event, files, input, form, iframe, uploadSettings, index);
                    }
                }).appendTo(form);
            },

            canHandleXHRUploadSize = function (files) {
                var bytes = 0,
                    totalBytes = 0,
                    i;
                if (settings.multipart && typeof FormData === undef) {
                    for (i = 0; i < files.length; i += 1) {
                        bytes = files[i].size;
                        if (bytes > settings.maxFileReaderSize) {
                            return false;
                        }
                        totalBytes += bytes;
                    }
                    if (settings.multiFileRequest && totalBytes > settings.maxFileReaderSize) {
                        return false;
                    }
                }
                return true;
            },

            handleFiles = function (event, files, input, form) {
                if (!canHandleXHRUploadSize(files)) {
                    handleLegacyUpload(event, input, form);
                    return;
                }
                var i;
                files = Array.prototype.slice.call(files, 0);
                $.each(files, normalizeFile);
                if (settings.multiFileRequest && settings.multipart && files.length) {
                    handleUpload(event, files, input, form);
                } else {
                    for (i = 0; i < files.length; i += 1) {
                        handleUpload(event, files, input, form, i);
                    }
                }
            },
            
            initUploadForm = function () {
                uploadForm = (container.is('form') ? container : container.find('form'))
                    .filter(settings.uploadFormFilter);
            },
            
            initFileInput = function () {
                fileInput = (uploadForm.length ? uploadForm : container).find('input:file')
                    .filter(settings.fileInputFilter);
            },
            
            replaceFileInput = function (input) {
                var inputClone = input.clone(true);
                $('<form/>').append(inputClone).get(0).reset();
                input.after(inputClone).detach();
                initFileInput();
            };

        this.onDocumentDragOver = function (e) {
            if (typeof settings.onDocumentDragOver === func &&
                    settings.onDocumentDragOver(e) === false) {
                return false;
            }
            e.preventDefault();
        };
        
        this.onDocumentDrop = function (e) {
            if (typeof settings.onDocumentDrop === func &&
                    settings.onDocumentDrop(e) === false) {
                return false;
            }
            e.preventDefault();
        };

        this.onDragOver = function (e) {
            if (typeof settings.onDragOver === func &&
                    settings.onDragOver(e) === false) {
                return false;
            }
            var dataTransfer = e.originalEvent.dataTransfer;
            if (dataTransfer && dataTransfer.files) {
                dataTransfer.dropEffect = dataTransfer.effectAllowed = 'copy';
                e.preventDefault();
            }
        };

        this.onDrop = function (e) {
            if (typeof settings.onDrop === func &&
                    settings.onDrop(e) === false) {
                return false;
            }
            var dataTransfer = e.originalEvent.dataTransfer;
            if (dataTransfer && dataTransfer.files && isXHRUploadCapable()) {
                handleFiles(e, dataTransfer.files);
            }
            e.preventDefault();
        };
        
        this.onChange = function (e) {
            if (typeof settings.onChange === func &&
                    settings.onChange(e) === false) {
                return false;
            }
            var input = $(e.target),
                form = $(e.target.form);
            if (form.length === 1) {
                if (settings.replaceFileInput) {
                    input.data(defaultNamespace + '_form', form);
                    replaceFileInput(input);
                }
            } else {
                form = input.data(defaultNamespace + '_form');
            }
            if (!settings.forceIframeUpload && e.target.files && isXHRUploadCapable()) {
                handleFiles(e, e.target.files, input, form);
            } else {
                handleLegacyUpload(e, input, form);
            }
        };

        this.init = function (options) {
            if (options) {
                $.extend(settings, options);
                optionsReference = options;
            }
            initUploadForm();
            initFileInput();
            if (container.data(settings.namespace)) {
                $.error('FileUpload with namespace "' + settings.namespace + '" already assigned to this element');
                return;
            }
            container
                .data(settings.namespace, fileUpload)
                .addClass(settings.cssClass);
            settings.dropZone.not(container).addClass(settings.cssClass);
            initEventHandlers();
            if (typeof settings.init === func) {
                settings.init();
            }
        };

        this.options = function (options) {
            var oldCssClass,
                oldDropZone,
                uploadFormFilterUpdate,
                fileInputFilterUpdate;
            if (typeof options === undef) {
                return $.extend({}, settings);
            }
            if (optionsReference) {
                $.extend(optionsReference, options);
            }
            removeEventHandlers();
            $.each(options, function (name, value) {
                switch (name) {
                case 'namespace':
                    $.error('The FileUpload namespace cannot be updated.');
                    return;
                case 'uploadFormFilter':
                    uploadFormFilterUpdate = true;
                    fileInputFilterUpdate = true;
                    break;
                case 'fileInputFilter':
                    fileInputFilterUpdate = true;
                    break;
                case 'cssClass':
                    oldCssClass = settings.cssClass;
                    break;
                case 'dropZone':
                    oldDropZone = settings.dropZone;
                    break;
                }
                settings[name] = value;
            });
            if (uploadFormFilterUpdate) {
                initUploadForm();
            }
            if (fileInputFilterUpdate) {
                initFileInput();
            }
            if (typeof oldCssClass !== undef) {
                container
                    .removeClass(oldCssClass)
                    .addClass(settings.cssClass);
                (oldDropZone ? oldDropZone : settings.dropZone).not(container)
                    .removeClass(oldCssClass);
                settings.dropZone.not(container).addClass(settings.cssClass);
            } else if (oldDropZone) {
                oldDropZone.not(container).removeClass(settings.cssClass);
                settings.dropZone.not(container).addClass(settings.cssClass);
            }
            initEventHandlers();
        };
        
        this.option = function (name, value) {
            var options;
            if (typeof value === undef) {
                return settings[name];
            }
            options = {};
            options[name] = value;
            fileUpload.options(options);
        };
        
        this.destroy = function () {
            if (typeof settings.destroy === func) {
                settings.destroy();
            }
            removeEventHandlers();
            container
                .removeData(settings.namespace)
                .removeClass(settings.cssClass);
            settings.dropZone.not(container).removeClass(settings.cssClass);
        };
        
        this.upload = function (files) {
            if (typeof files.length === undef) {
                files = [files];
            }
            handleFiles(null, files);
        };
    };

    methods = {
        init : function (options) {
            return this.each(function () {
                (new FileUpload($(this))).init(options);
            });
        },
        
        option: function (option, value, namespace) {
            namespace = namespace ? namespace : defaultNamespace;
            var fileUpload = $(this).data(namespace);
            if (fileUpload) {
                if (!option) {
                    return fileUpload.options();
                } else if (typeof option === 'string' && typeof value === undef) {
                    return fileUpload.option(option);
                }
            } else {
                $.error('No FileUpload with namespace "' + namespace + '" assigned to this element');
            }
            return this.each(function () {
                var fu = $(this).data(namespace);
                if (fu) {
                    if (typeof option === 'string') {
                        fu.option(option, value);
                    } else {
                        fu.options(option);
                    }
                } else {
                    $.error('No FileUpload with namespace "' + namespace + '" assigned to this element');
                }
            });
        },
                
        destroy: function (namespace) {
            namespace = namespace ? namespace : defaultNamespace;
            return this.each(function () {
                var fileUpload = $(this).data(namespace);
                if (fileUpload) {
                    fileUpload.destroy();
                } else {
                    $.error('No FileUpload with namespace "' + namespace + '" assigned to this element');
                }
            });
        },
        
        upload: function (files, namespace) {
            namespace = namespace ? namespace : defaultNamespace;
            return this.each(function () {
                var fileUpload = $(this).data(namespace);
                if (fileUpload) {
                    fileUpload.upload(files);
                } else {
                    $.error('No FileUpload with namespace "' + namespace + '" assigned to this element');
                }
            });
        }
    };
    
    $.fn.fileUpload = function (method) {
        if (methods[method]) {
            return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof method === 'object' || !method) {
            return methods.init.apply(this, arguments);
        } else {
            $.error('Method "' + method + '" does not exist on jQuery.fileUpload');
        }
    };
    
}(jQuery));


/*
 * jQuery File Upload User Interface Plugin 4.4.1
 * https://github.com/blueimp/jQuery-File-Upload
 *
 * Copyright 2010, Sebastian Tschan
 * https://blueimp.net
 *
 * Licensed under the MIT license:
 * http://creativecommons.org/licenses/MIT/
 */

/*jslint browser: true, unparam: true */
/*global jQuery, FileReader, URL, webkitURL */

(function ($) {
    'use strict';

    var undef = 'undefined',
        func = 'function',
        UploadHandler,
        methods,

        MultiLoader = function (callBack) {
            var loaded = 0,
                list = [];
            this.complete = function () {
                loaded += 1;
                if (loaded === list.length + 1) {
                    // list.length * onComplete + 1 * onLoadAll
                    callBack(list);
                    loaded = 0;
                    list = [];
                }
            };
            this.push = function (item) {
                list.push(item);
            };
            this.getList = function () {
                return list;
            };
        };
        
    UploadHandler = function (container, options) {
        var uploadHandler = this,
            dragOverTimeout,
            isDropZoneEnlarged,
            multiLoader = new MultiLoader(function (list) {
                uploadHandler.hideProgressBarAll(function () {
                    uploadHandler.resetProgressBarAll();
                    if (typeof uploadHandler.onCompleteAll === func) {
                        uploadHandler.onCompleteAll(list);
                    }
                });
            }),
            getUploadTable = function (handler) {
                return typeof handler.uploadTable === func ?
                    handler.uploadTable(handler) : handler.uploadTable;
            },
            getDownloadTable = function (handler) {
                return typeof handler.downloadTable === func ?
                    handler.downloadTable(handler) : handler.downloadTable;
            };
        
        this.requestHeaders = {'Accept': 'application/json, text/javascript, */*; q=0.01'};
        this.dropZone = container;
        this.imageTypes = /^image\/(gif|jpeg|png)$/;
        this.previewMaxWidth = this.previewMaxHeight = 80;
        this.previewLoadDelay = 100;
        this.previewAsCanvas = true;
        this.previewSelector = '.file_upload_preview';
        this.progressSelector = '.file_upload_progress div';
        this.cancelSelector = '.file_upload_cancel button';
        this.cssClassSmall = 'file_upload_small';
        this.cssClassLarge = 'file_upload_large';
        this.cssClassHighlight = 'file_upload_highlight';
        this.dropEffect = 'highlight';
        this.uploadTable = this.downloadTable = null;
        this.buildUploadRow = this.buildDownloadRow = null;
        this.progressAllNode = null;

        this.loadImage = function (file, callBack, maxWidth, maxHeight, imageTypes, noCanvas) {
            var img,
                scaleImage,
                urlAPI,
                fileReader;
            if (imageTypes && !imageTypes.test(file.type)) {
                return null;
            }
            scaleImage = function (img) {
                var canvas = document.createElement('canvas'),
                    scale = Math.min(
                        (maxWidth || img.width) / img.width,
                        (maxHeight || img.height) / img.height
                    );
                if (scale > 1) {
                    scale = 1;
                }
                img.width = parseInt(img.width * scale, 10);
                img.height = parseInt(img.height * scale, 10);
                if (noCanvas || typeof canvas.getContext !== func) {
                    return img;
                }
                canvas.width = img.width;
                canvas.height = img.height;
                canvas.getContext('2d').drawImage(img, 0, 0, img.width, img.height);
                return canvas;
            };
            img = document.createElement('img');
            urlAPI = typeof URL !== undef ? URL : typeof webkitURL !== undef ? webkitURL : null;
            if (urlAPI && typeof urlAPI.createObjectURL === func) {
                img.onload = function () {
                    urlAPI.revokeObjectURL(this.src);
                    callBack(scaleImage(img));
                };
                img.src = urlAPI.createObjectURL(file);
            } else if (typeof FileReader !== undef &&
                    typeof FileReader.prototype.readAsDataURL === func) {
                img.onload = function () {
                    callBack(scaleImage(img));
                };
                fileReader = new FileReader();
                fileReader.onload = function (e) {
                    img.src = e.target.result;
                };
                fileReader.readAsDataURL(file);
            } else {
                callBack(null);
            }
        };

        this.addNode = function (parentNode, node, callBack) {
            if (parentNode && parentNode.length && node && node.length) {
                node.css('display', 'none').appendTo(parentNode).fadeIn(function () {
                    if (typeof callBack === func) {
                        try {
                            callBack();
                        } catch (e) {
                            // Fix endless exception loop:
                            node.stop();
                            throw e;
                        }
                    }
                });
            } else if (typeof callBack === func) {
                callBack();
            }
        };

        this.removeNode = function (node, callBack) {
            if (node && node.length) {
                node.fadeOut(function () {
                    node.remove();
                    if (typeof callBack === func) {
                        try {
                            callBack();
                        } catch (e) {
                            // Fix endless exception loop:
                            node.stop();
                            throw e;
                        }
                    }
                });
            } else if (typeof callBack === func) {
                callBack();
            }
        };
        
        this.replaceNode = function (oldNode, newNode, callBack) {
            if (!(newNode && newNode.length)) {
                return uploadHandler.removeNode(oldNode, callBack);
            }
            if (oldNode && oldNode.length) {
                oldNode.fadeOut(function () {
                    newNode.css('display', 'none');
                    oldNode.replaceWith(newNode);
                    newNode.fadeIn(function () {
                        if (typeof callBack === func) {
                            try {
                                callBack();
                            } catch (e) {
                                // Fix endless exception loop:
                                oldNode.stop();
                                newNode.stop();
                                throw e;
                            }
                        }
                    });
                });
            } else if (typeof callBack === func) {
                callBack();
            }
        };

        this.resetProgressBarAll = function () {
            if (uploadHandler.progressbarAll) {
                uploadHandler.progressbarAll.progressbar(
                    'value',
                    0
                );
            }
        };

        this.hideProgressBarAll = function (callBack) {
            if (uploadHandler.progressbarAll && !$(getUploadTable(uploadHandler))
                    .find(uploadHandler.progressSelector + ':visible:first').length) {
                uploadHandler.progressbarAll.fadeOut(callBack);
            } else if (typeof callBack === func) {
                callBack();
            }
        };

        this.onAbort = function (event, files, index, xhr, handler) {
            handler.removeNode(handler.uploadRow, handler.hideProgressBarAll);
        };
        
        this.cancelUpload = function (event, files, index, xhr, handler) {
            var readyState = xhr.readyState;
            xhr.abort();
            // If readyState is below 2, abort() has no effect:
            if (typeof readyState !== 'number' || readyState < 2) {
                handler.onAbort(event, files, index, xhr, handler);
            }
        };
        
        this.initProgressBar = function (node, value) {
            if (!node || !node.length) {
                return null;
            }
            if (typeof node.progressbar === func) {
                return node.progressbar({
                    value: value
                });
            } else {
                node.addClass('progressbar')
                    .append($('<div/>').css('width', value + '%'))
                    .progressbar = function (key, value) {
                        return this.each(function () {
                            if (key === 'destroy') {
                                $(this).removeClass('progressbar').empty();
                            } else {
                                $(this).children().css('width', value + '%');
                            }
                        });
                    };
                return node;
            }
        };
        
        this.destroyProgressBar = function (node) {
            if (!node || !node.length) {
                return null;
            }
            return node.progressbar('destroy');
        };
        
        this.initUploadProgress = function (xhr, handler) {
            if (!xhr.upload && handler.progressbar) {
                handler.progressbar.progressbar(
                    'value',
                    100 // indeterminate progress displayed by a full animated progress bar
                );
            }
        };

        this.initUploadProgressAll = function () {
            if (uploadHandler.progressbarAll && uploadHandler.progressbarAll.is(':hidden')) {
                uploadHandler.progressbarAll.fadeIn();
            }
        };

        this.onSend = function (event, files, index, xhr, handler) {
            handler.initUploadProgress(xhr, handler);
        };

        this.onProgress = function (event, files, index, xhr, handler) {
            if (handler.progressbar && event.lengthComputable) {
                handler.progressbar.progressbar(
                    'value',
                    parseInt(event.loaded / event.total * 100, 10)
                );
            }
        };

        this.onProgressAll = function (event, list) {
            if (uploadHandler.progressbarAll && event.lengthComputable) {
                uploadHandler.progressbarAll.progressbar(
                    'value',
                    parseInt(event.loaded / event.total * 100, 10)
                );
            }
        };

        this.onLoadAll = function (list) {
            multiLoader.complete();
        };

        this.initProgressBarAll = function () {
            if (!uploadHandler.progressbarAll) {
                uploadHandler.progressbarAll = uploadHandler.initProgressBar(
                    (typeof uploadHandler.progressAllNode === func ?
                    uploadHandler.progressAllNode(uploadHandler) : uploadHandler.progressAllNode),
                    0
                );
            }
        };
        
        this.destroyProgressBarAll = function () {
            uploadHandler.destroyProgressBar(uploadHandler.progressbarAll);
        };

        this.loadPreviewImage = function (files, index, handler) {
            index = index || 0;
            handler.uploadRow.find(handler.previewSelector).each(function () {
                var previewNode = $(this),
                    file = files[index];
                setTimeout(function () {
                    handler.loadImage(
                        file,
                        function (img) {
                            handler.addNode(
                                previewNode,
                                $(img)
                            );
                        },
                        handler.previewMaxWidth,
                        handler.previewMaxHeight,
                        handler.imageTypes,
                        !handler.previewAsCanvas
                    );
                }, handler.previewLoadDelay);
                index += 1;
            });
        };

        this.initUploadRow = function (event, files, index, xhr, handler) {
            var uploadRow = handler.uploadRow = (typeof handler.buildUploadRow === func ?
                handler.buildUploadRow(files, index, handler) : null);
            if (uploadRow) {
                handler.progressbar = handler.initProgressBar(
                    uploadRow.find(handler.progressSelector),
                    0
                );
                uploadRow.find(handler.cancelSelector).click(function (e) {
                    handler.cancelUpload(e, files, index, xhr, handler);
                    e.preventDefault();
                });
                handler.loadPreviewImage(files, index, handler);
            }
        };
        
        this.initUpload = function (event, files, index, xhr, handler, callBack) {
            handler.initUploadRow(event, files, index, xhr, handler);
            handler.addNode(
                getUploadTable(handler),
                handler.uploadRow,
                function () {
                    if (typeof handler.beforeSend === func) {
                        handler.beforeSend(event, files, index, xhr, handler, callBack);
                    } else {
                        callBack();
                    }
                }
            );
            handler.initUploadProgressAll();
        };
        
        this.parseResponse = function (xhr, handler) {
            if (typeof xhr.responseText !== undef) {
                return $.parseJSON(xhr.responseText);
            } else {
                // Instead of an XHR object, an iframe is used for legacy browsers:
                return $.parseJSON(xhr.contents().text());
            }
        };
        
        this.initDownloadRow = function (event, files, index, xhr, handler) {
            var json, downloadRow;
            try {
                json = handler.response = handler.parseResponse(xhr, handler);
            } catch (e) {
                if (typeof handler.onError === func) {
                    handler.originalEvent = event;
                    handler.onError(e, files, index, xhr, handler);
                } else {
                    throw e;
                }
            }
            downloadRow = handler.downloadRow = (typeof handler.buildDownloadRow === func ?
                handler.buildDownloadRow(json, handler) : null);
        };
        
        this.onLoad = function (event, files, index, xhr, handler) {
            var uploadTable = getUploadTable(handler),
                downloadTable = getDownloadTable(handler),
                callBack = function () {
                    if (typeof handler.onComplete === func) {
                        handler.onComplete(event, files, index, xhr, handler);
                    }
                    multiLoader.complete();
                };
            multiLoader.push(Array.prototype.slice.call(arguments, 1));
            handler.initDownloadRow(event, files, index, xhr, handler);
            if (uploadTable && handler.uploadRow && handler.uploadRow.length &&
                    (!downloadTable || uploadTable.get(0) === downloadTable.get(0))) {
                handler.replaceNode(handler.uploadRow, handler.downloadRow, callBack);
            } else {
                handler.removeNode(handler.uploadRow, function () {
                    handler.addNode(
                        downloadTable,
                        handler.downloadRow,
                        callBack
                    );
                });
            }
        };

        this.dropZoneEnlarge = function () {
            if (!isDropZoneEnlarged) {
                if (typeof uploadHandler.dropZone.switchClass === func) {
                    uploadHandler.dropZone.switchClass(
                        uploadHandler.cssClassSmall,
                        uploadHandler.cssClassLarge
                    );
                } else {
                    uploadHandler.dropZone.addClass(uploadHandler.cssClassLarge);
                    uploadHandler.dropZone.removeClass(uploadHandler.cssClassSmall);
                }
                isDropZoneEnlarged = true;
            }
        };
        
        this.dropZoneReduce = function () {
            if (typeof uploadHandler.dropZone.switchClass === func) {
                uploadHandler.dropZone.switchClass(
                    uploadHandler.cssClassLarge,
                    uploadHandler.cssClassSmall
                );
            } else {
                uploadHandler.dropZone.addClass(uploadHandler.cssClassSmall);
                uploadHandler.dropZone.removeClass(uploadHandler.cssClassLarge);
            }
            isDropZoneEnlarged = false;
        };

        this.onDocumentDragEnter = function (event) {
            uploadHandler.dropZoneEnlarge();
        };
        
        this.onDocumentDragOver = function (event) {
            if (dragOverTimeout) {
                clearTimeout(dragOverTimeout);
            }
            dragOverTimeout = setTimeout(function () {
                uploadHandler.dropZoneReduce();
            }, 200);
        };
        
        this.onDragEnter = this.onDragLeave = function (event) {
            uploadHandler.dropZone.toggleClass(uploadHandler.cssClassHighlight);
        };
        
        this.onDrop = function (event) {
            if (dragOverTimeout) {
                clearTimeout(dragOverTimeout);
            }
            if (uploadHandler.dropEffect && typeof uploadHandler.dropZone.effect === func) {
                uploadHandler.dropZone.effect(uploadHandler.dropEffect, function () {
                    uploadHandler.dropZone.removeClass(uploadHandler.cssClassHighlight);
                    uploadHandler.dropZoneReduce();
                });
            } else {
                uploadHandler.dropZone.removeClass(uploadHandler.cssClassHighlight);
                uploadHandler.dropZoneReduce();
            }
        };

        this.init = function () {
            uploadHandler.initProgressBarAll();
            if (typeof uploadHandler.initExtended === func) {
                uploadHandler.initExtended();
            }
        };
        
        this.destroy = function () {
            if (typeof uploadHandler.destroyExtended === func) {
                uploadHandler.destroyExtended();
            }
            uploadHandler.destroyProgressBarAll();
        };

        $.extend(this, options);
    };

    methods = {
        init : function (options) {
            return this.each(function () {
                $(this).fileUpload(new UploadHandler($(this), options));
            });
        },
        
        option: function (option, value, namespace) {
            if (!option || (typeof option === 'string' && typeof value === undef)) {
                return $(this).fileUpload('option', option, value, namespace);
            }
            return this.each(function () {
                $(this).fileUpload('option', option, value, namespace);
            });
        },
            
        destroy : function (namespace) {
            return this.each(function () {
                $(this).fileUpload('destroy', namespace);
            });
        },
        
        upload: function (files, namespace) {
            return this.each(function () {
                $(this).fileUpload('upload', files, namespace);
            });
        }
    };
    
    $.fn.fileUploadUI = function (method) {
        if (methods[method]) {
            return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof method === 'object' || !method) {
            return methods.init.apply(this, arguments);
        } else {
            $.error('Method "' + method + '" does not exist on jQuery.fileUploadUI');
        }
    };
    
}(jQuery));

/*
 * jQuery File Upload User Interface Extended Plugin 4.6
 * https://github.com/blueimp/jQuery-File-Upload
 *
 * Copyright 2010, Sebastian Tschan
 * https://blueimp.net
 *
 * Licensed under the MIT license:
 * http://creativecommons.org/licenses/MIT/
 */

/*jslint regexp: false, unparam: true */
/*global jQuery */

(function ($) {
    'use strict';

    var UploadHandler,
        methods;

    // Emulate jQuery UI button (without states) if not available:
    if (typeof $().button !== 'function') {
        $.fn.button = function (options) {
            return this.each(function () {
                if (options === 'destroy') {
                    $(this).removeClass(
                        'ui-button ui-widget ui-state-default ui-corner-all' +
                            ' ui-button-icon-only ui-button-text-icon-primary'
                    ).html($(this).text());
                } else {
                    $(this)
                        .addClass('ui-button ui-widget ui-state-default ui-corner-all')
                        .addClass(
                            options.text === false ? 'ui-button-icon-only' :
                                'ui-button-text-icon-primary'
                        )
                        .html($('<span class="ui-button-text"/>').text($(this).text()))
                        .prepend(
                            $('<span class="ui-button-icon-primary ui-icon"/>')
                                .addClass(options.icons.primary)
                        );
                }
            });
        };
    }
        
    UploadHandler = function (container, options) {
        var uploadHandler = this;

        this.locale = {};
        this.maxFileSize = null;
        this.minFileSize = 1;
        this.maxNumberOfFiles = null;
        this.acceptFileTypes = /.+$/i;
        this.autoUpload = false;
        this.forceIframeDownload = true;
        this.url = container.find('form:first').attr('action');
        this.dropZone = container.find('form:first');
        this.uploadTable = container.find('.files:first');
        this.downloadTable = this.uploadTable;
        this.progressAllNode = container.find('.file_upload_overall_progress div:first');
        this.uploadTemplate = this.uploadTable.find('.file_upload_template:first');
        this.downloadTemplate = this.uploadTable.find('.file_download_template:first');
        this.multiButtons = container.find('.file_upload_buttons:first');
        
        this.adjustMaxNumberOfFiles = function (operand) {
            var number = container.fileUploadUIX('option', 'maxNumberOfFiles');
            if (typeof number === 'number') {
                container.fileUploadUIX('option', 'maxNumberOfFiles', number + operand);
            }
        };
        
        this.formatFileSize = function (bytes) {
            if (typeof bytes !== 'number' || bytes === null) {
                return '';
            }
            if (bytes >= 1000000000) {
                return (bytes / 1000000000).toFixed(2) + ' GB';
            }
            if (bytes >= 1000000) {
                return (bytes / 1000000).toFixed(2) + ' MB';
            }
            return (bytes / 1000).toFixed(2) + ' KB';
        };
        
        this.formatFileName = function (name) {
            return name.replace(/^.*[\/\\]/, '');
        };
        
        this.enableDragToDesktop = function () {
            var link = $(this),
                url = link.get(0).href,
                name = decodeURIComponent(url.split('/').pop()).replace(/:/g, '-'),
                type = 'application/octet-stream';
            link.bind('dragstart', function (event) {
                try {
                    event.originalEvent.dataTransfer
                        .setData('DownloadURL', [type, name, url].join(':'));
                } catch (e) {}
            });
        };

        this.buildMultiUploadRow = function (files, handler) {
            var rows = $('<tbody style="display:none;"/>');
            $.each(files, function (index, file) {
                var row = handler.buildUploadRow(files, index, handler).show(),
                    cells = row.find(
                        '.file_upload_progress, .file_upload_start, .file_upload_cancel'
                    );
                if (index) {
                    cells.remove();
                } else {
                    cells.attr('rowspan', files.length);
                }
                rows.append(row);
            });
            return rows;
        };

        this.buildUploadRow = function (files, index, handler) {
            if (typeof index !== 'number') {
                return handler.buildMultiUploadRow(files, handler);
            }
            var file = files[index],
                fileName = handler.formatFileName(file.name),
                uploadRow = handler.uploadTemplate
                    .clone().removeAttr('id');
            uploadRow.find('.file_name')
                .text(fileName);
            uploadRow.find('.file_size')
                .text(handler.formatFileSize(file.size));
            if (handler.autoUpload) {
                uploadRow.find('.file_upload_start button').hide();
            } else {
                uploadRow.find('.file_upload_start button')
                    .button({icons: {primary: 'ui-icon-circle-arrow-e'}, text: false});
            }
            uploadRow.find('.file_upload_cancel button')
                .button({icons: {primary: 'ui-icon-cancel'}, text: false});
            return uploadRow;
        };

        this.getFileUrl = function (file, handler) {
            return file.url;
        };
        
        this.getThumbnailUrl = function (file, handler) {
            return file.thumbnail;
        };

        this.buildMultiDownloadRow = function (files, handler) {
            var rows = $('<tbody style="display:none;"/>');
            $.each(files, function (index, file) {
                rows.append(handler.buildDownloadRow(file, handler).show());
            });
            return rows;
        };

        this.buildDownloadRow = function (file, handler) {
            if ($.isArray(file)) {
                return handler.buildMultiDownloadRow(file, handler);
            }
            var fileName = handler.formatFileName(file.name),
                fileUrl = handler.getFileUrl(file, handler),
                thumbnailUrl = handler.getThumbnailUrl(file, handler),
                downloadRow = handler.downloadTemplate
                    .clone().removeAttr('id');
            downloadRow.attr('data-id', file.id || file.name);
            downloadRow.find('.file_name a')
                .text(fileName);
            downloadRow.find('.file_size')
                .text(handler.formatFileSize(file.size));
            if (thumbnailUrl) {
                downloadRow.find('.file_download_preview').append(
                    $('<a/>').append($('<img/>').attr('src', thumbnailUrl))
                );
                downloadRow.find('a').attr('target', '_blank');
            }
            downloadRow.find('a')
                .attr('href', fileUrl || null)
                .each(handler.enableDragToDesktop);
            downloadRow.find('.file_download_delete button')
                .button({icons: {primary: 'ui-icon-trash'}, text: false});
            return downloadRow;
        };
        
        this.onError = function (event, files, index, xhr, handler) {
            handler.uploadRow.addClass('file_upload_error')
                .find('.file_upload_progress').append($('<div class="error"/>').append(
                    handler.locale[event] || event
                ));
        };
        
        this.validate = function (event, files, index, xhr, handler) {
            var isValid = true,
                file;
            if (typeof index !== 'number') {
                $.each(files, function (index, file) {
                    isValid = handler.validate(event, files, index, xhr, handler);
                });
            } else {
                file = files[index];
                if (handler.maxFileSize && file.size > handler.maxFileSize) {
                    handler.onError('File is too big', files, index, xhr, handler);
                    isValid = false;
                } else if (typeof file.size === 'number' && file.size < handler.minFileSize) {
                    handler.onError('File is too small', files, index, xhr, handler);
                    isValid = false;
                }
                if (!(handler.acceptFileTypes.test(file.type) ||
                        handler.acceptFileTypes.test(file.name))) {
                    handler.onError('Filetype not allowed', files, index, xhr, handler);
                    isValid = false;
                }
                if (typeof handler.maxNumberOfFiles === 'number' &&
                        handler.maxNumberOfFiles < index + 1) {
                    handler.onError('Max number exceeded', files, index, xhr, handler);
                    isValid = false;
                }
            }
            return isValid;
        };

        this.uploadCallBack = function (event, files, index, xhr, handler, callBack) {
            callBack();
        };
        
        this.beforeSend = function (event, files, index, xhr, handler, callBack) {
            if (!handler.validate(event, files, index, xhr, handler)) {
                return;
            }
            var number = typeof index === 'number' ? 1 : files.length;
            handler.adjustMaxNumberOfFiles(-number);
            handler.uploadRow.find(handler.cancelSelector).click(function (e) {
                handler.adjustMaxNumberOfFiles(number);
            });
            if (handler.autoUpload) {
                handler.uploadCallBack(event, files, index, xhr, handler, callBack);
            } else {
                handler.uploadRow.find('.file_upload_start button').click(function (e) {
                    $(this).fadeOut();
                    handler.uploadCallBack(event, files, index, xhr, handler, callBack);
                    e.preventDefault();
                });
            }
        };

        this.downloadHandler = function (e) {
            if (uploadHandler.forceIframeDownload) {
                // Open download dialogs via iframes, to prevent aborting current uploads:
                $('<iframe style="display:none;"/>')
                    .attr('src', this.href)
                    .appendTo(container);
                e.preventDefault();
            }
        };

        this.deleteHandler = function (e) {
            var row = $(this).closest('tr');
            $.ajax({
                url: uploadHandler.url + '?file=' + encodeURIComponent(
                    row.attr('data-id')
                ),
                type: 'DELETE',
                success: function () {
                    uploadHandler.adjustMaxNumberOfFiles(1);
                    row.fadeOut(function () {
                        row.remove();
                    });
                }
            });
            e.preventDefault();
        };
        
        this.initEventHandlers = function () {
            uploadHandler.downloadTable.find('a:not([target="_blank"])')
                .live('click', uploadHandler.downloadHandler);
            uploadHandler.downloadTable.find('.file_download_delete button')
                .live('click', uploadHandler.deleteHandler);
        };

        this.destroyEventHandlers = function () {
            uploadHandler.downloadTable.find('a:not([target="_blank"])')
                .die('click', uploadHandler.downloadHandler);
            uploadHandler.downloadTable.find('.file_download_delete button')
                .die('click', uploadHandler.deleteHandler);
        };
        
        this.multiButtonHandler = function (e) {
            uploadHandler.uploadTable.find(e.data.selector + ' button:visible').click();
            e.preventDefault();
        };
        
        this.initMultiButtons = function () {
            if (uploadHandler.autoUpload) {
                uploadHandler.multiButtons.find('.file_upload_start:first').hide();
            } else {
                uploadHandler.multiButtons.find('.file_upload_start:first')
                    .button({icons: {primary: 'ui-icon-circle-arrow-e'}})
                    .bind('click', {selector: '.file_upload_start'}, uploadHandler.multiButtonHandler);
            }
            uploadHandler.multiButtons.find('.file_upload_cancel:first')
                .button({icons: {primary: 'ui-icon-cancel'}})
                .bind('click', {selector: '.file_upload_cancel'}, uploadHandler.multiButtonHandler);
            uploadHandler.multiButtons.find('.file_download_delete:first')
                .button({icons: {primary: 'ui-icon-trash'}})
                .bind('click', {selector: '.file_download_delete'}, uploadHandler.multiButtonHandler);
        };
        
        this.destroyMultiButtons = function () {
            uploadHandler.multiButtons.find(
                '.file_upload_start:first, .file_upload_cancel:first, .file_download_delete:first'
            ).unbind('click', uploadHandler.multiButtonHandler).button('destroy').show();
        };

        this.initExtended = function () {
            uploadHandler.initEventHandlers();
            uploadHandler.initMultiButtons();
        };

        this.destroyExtended = function () {
            uploadHandler.destroyEventHandlers();
            uploadHandler.destroyMultiButtons();
        };

        $.extend(this, options);
    };

    methods = {
        init : function (options) {
            return this.each(function () {
                $(this).fileUploadUI(new UploadHandler($(this), options));
            });
        },
        
        option: function (option, value, namespace) {
            if (!option || (typeof option === 'string' && typeof value === 'undefined')) {
                return $(this).fileUpload('option', option, value, namespace);
            }
            return this.each(function () {
                $(this).fileUploadUI('option', option, value, namespace);
            });
        },
            
        destroy : function (namespace) {
            return this.each(function () {
                $(this).fileUploadUI('destroy', namespace);
            });
        },
        
        upload: function (files, namespace) {
            return this.each(function () {
                $(this).fileUploadUI('upload', files, namespace);
            });
        }
    };
    
    $.fn.fileUploadUIX = function (method) {
        if (methods[method]) {
            return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof method === 'object' || !method) {
            return methods.init.apply(this, arguments);
        } else {
            $.error('Method "' + method + '" does not exist on jQuery.fileUploadUIX');
        }
    };
    
}(jQuery));

$('#file_upload').fileUploadUIX({
  'dropZone': $('div.files'),
  'autoUpload':true,
  'onLoad': function(event, files, index, xhr, handler) {
    var json;
    if (typeof xhr.responseText !== 'undefined') {
        json = $.parseJSON(xhr.responseText);
    } else {
        // Instead of an XHR object, an iframe is used for legacy browsers:
        json = $.parseJSON(xhr.contents().text());
    }
    
    var s='<div class="file '+json.type.replace(/\/.*/, '')+' unpublished">';
    s+='<a id="e'+json.id+'" class="edit" style="background:url(\''+json.url+'\') no-repeat;" href="'+json.link+'">'+json.name+'</a>';
    s+='</div>';
    $('.tdz div.files>div').last().after(s);
    var fn=tdz.file_action;
    if($('.tdz .toolbar').length==0) fn=tdz.mediaSelect;
    $('a#e'+json.id).unbind('click').click(fn);
  }
});
