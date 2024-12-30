#!/bin/bash
docker-compose down --volumes --remove-orphans
docker system prune -a