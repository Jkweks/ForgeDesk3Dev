FROM node:20-alpine

# serialport uses native bindings; these let it build from source on musl
# (alpine) if a prebuilt binary isn't available for this platform.
RUN apk add --no-cache python3 make g++ linux-headers eudev-dev

WORKDIR /app
COPY package*.json ./
RUN npm install --omit=dev

COPY . .

EXPOSE 9111
CMD ["node", "server.js"]
