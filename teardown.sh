#!/bin/bash
docker-compose down --rmi all --volumes --remove-orphans
docker system prune -a