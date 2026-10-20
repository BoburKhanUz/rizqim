// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'pagination.g.dart';

@JsonSerializable()
class Pagination {
  const Pagination({
    required this.currentPage,
    required this.lastPage,
    required this.total,
    required this.perPage,
  });

  factory Pagination.fromJson(Map<String, Object?> json) =>
      _$PaginationFromJson(json);

  @JsonKey(name: 'current_page')
  final int currentPage;
  @JsonKey(name: 'last_page')
  final int lastPage;
  final int total;
  @JsonKey(name: 'per_page')
  final int perPage;

  Map<String, Object?> toJson() => _$PaginationToJson(this);
}
