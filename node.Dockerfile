ARG REGISTRY_URI

FROM $REGISTRY_URI/node:8.11.0

WORKDIR /var/www/logger

COPY ./package.* ./

RUN npm install

#UN apt-get update && apt-get install -y nano

COPY ./webpack.mix.js ./webpack.mix.js

COPY ./resources ./resources

COPY ./public ./public

# Copy existing application directory contents
COPY . .

RUN cp .env.example.json .env.json

# Copy existing application directory permissions
COPY --chown=root:www-data . /var/www/logger/public

CMD ["npm","run", "prod"]
