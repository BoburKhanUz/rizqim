// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'length_aware_paginator.g.dart';

@JsonSerializable()
class LengthAwarePaginator {
  const LengthAwarePaginator({
    required this.currentPage,
    required this.data,
    required this.firstPageUrl,
    required this.from,
    required this.lastPage,
    required this.lastPageUrl,
    required this.links,
    required this.nextPageUrl,
    required this.path,
    required this.perPage,
    required this.prevPageUrl,
    required this.to,
    required this.total,
  });

  factory LengthAwarePaginator.fromJson(Map<String, Object?> json) =>
      _$LengthAwarePaginatorFromJson(json);

  @JsonKey(name: 'current_page')
  final int currentPage;
  final String data;
  @JsonKey(name: 'first_page_url')
  final String firstPageUrl;
  final int? from;
  @JsonKey(name: 'last_page')
  final int lastPage;
  @JsonKey(name: 'last_page_url')
  final String lastPageUrl;
  final Map<String, dynamic> links;
  @JsonKey(name: 'next_page_url')
  final String nextPageUrl;
  final String? path;
  @JsonKey(name: 'per_page')
  final int perPage;
  @JsonKey(name: 'prev_page_url')
  final String? prevPageUrl;
  final int? to;
  final int total;

  Map<String, Object?> toJson() => _$LengthAwarePaginatorToJson(this);
}
