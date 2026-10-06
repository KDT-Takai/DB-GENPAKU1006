FROM mysql:8.0.36-debian

RUN rm -f /etc/apt/sources.list.d/mysql.list && \
    apt-get update && apt-get install -y locales
RUN sed -i 's/# ja_JP.UTF-8 UTF-8/ja_JP.UTF-8 UTF-8/' /etc/locale.gen && locale-gen
ENV LANG ja_JP.UTF-8
ENV LC_ALL ja_JP.UTF-8
ENV TZ Asia/Tokyo
