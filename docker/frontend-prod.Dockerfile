# Production image for the React frontend — static build served by nginx,
# which also reverse-proxies /api to the backend (same-origin, no CORS).
FROM node:22-alpine AS build
WORKDIR /app
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ .
# Same-origin API: axios hits /api/v1 and nginx proxies it.
ENV VITE_API_BASE_URL=/api/v1
RUN npm run build

FROM nginx:1.27-alpine
COPY docker/nginx-frontend.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
