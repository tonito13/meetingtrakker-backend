#!/bin/bash
# Simple script to execute SQL file
psql -U workmatica_user -d orgtrakker_100000 -f /tmp/insert.sql

