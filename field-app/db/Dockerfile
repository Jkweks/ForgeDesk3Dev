# Postgres with transport encryption on. The self-signed certificate is generated when the
# image is built, so ForgeDesk's connection (FIELDSYNC_DB_SSLMODE=require) is encrypted even
# across a network you don't fully control. It does not authenticate the server; keep the
# port firewalled to ForgeDesk's address regardless.
FROM postgres:16-alpine

RUN apk add --no-cache openssl \
 && mkdir -p /etc/postgresql/ssl \
 && openssl req -new -x509 -days 3650 -nodes -subj "/CN=forgedesk-field-db" \
      -keyout /etc/postgresql/ssl/server.key -out /etc/postgresql/ssl/server.crt \
 && chown postgres:postgres /etc/postgresql/ssl/server.* \
 && chmod 600 /etc/postgresql/ssl/server.key

COPY init/ /docker-entrypoint-initdb.d/
RUN chmod +x /docker-entrypoint-initdb.d/*.sh

CMD ["postgres", "-c", "ssl=on", \
     "-c", "ssl_cert_file=/etc/postgresql/ssl/server.crt", \
     "-c", "ssl_key_file=/etc/postgresql/ssl/server.key"]
