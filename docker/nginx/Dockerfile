# Stage 1: Frontend assets
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: Nginx
FROM nginx:alpine

RUN apk add --no-cache curl

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

COPY --from=frontend /app/public /app/public

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
