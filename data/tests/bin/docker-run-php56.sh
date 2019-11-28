#!/usr/bin/env bash

DOCKER_IMAGE='composer-php56'

[[ "$(docker images -q $DOCKER_IMAGE:latest 2> /dev/null)" == "" ]] && \
  docker build -t $DOCKER_IMAGE:latest --label=$DOCKER_IMAGE -f tests/bin/Dockerfile-php56 tests/bin

docker run --rm -v $(pwd):/opt/project:z -w /opt/project $DOCKER_IMAGE tests/bin/run.sh
exit 0;
