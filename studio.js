/*!Studio v2.5.1 */

const { app, BrowserWindow } = require('electron');
const S_root = app.getAppPath();
app.applicationMenu = null;

function createWindow () {
    var t='splash';


    const win = new BrowserWindow({
        //skipTaskbar: true,
        //type: t,
        icon: S_root+'/data/web/favicon.png',
        backgroundColor: '#ff0099',
        width: 600,
        height: 600
    });

    //win.loadFile('index.html')
    /*
    checklist:

    - check/download php+modules
    - check/download composer
    - check/download git (optional?)
    - run initial configuration options
    - start web view 127.0.0.1:9999
    - keep background process, listening to local file socket (would this work on windows?)
    */
    win.loadURL('http://127.0.0.1:9999/_studio');
}

app.whenReady().then(() => {
  createWindow()

  app.on('activate', function () {
    if (BrowserWindow.getAllWindows().length === 0) createWindow()
  })
})

app.on('window-all-closed', function () {
  if (process.platform !== 'darwin') app.quit()
})

