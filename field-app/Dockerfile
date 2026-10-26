FROM node:20-alpine
ENV NODE_ENV=production
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --omit=dev --no-audit --no-fund
COPY server ./server
COPY scripts ./scripts
COPY public ./public
USER node
EXPOSE 3000
CMD ["node", "server/index.js"]
