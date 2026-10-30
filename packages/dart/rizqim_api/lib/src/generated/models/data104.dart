// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data103.dart';

part 'data104.g.dart';

@JsonSerializable()
class Data104 {
  const Data104({
    required this.currentPage,
    required this.data,
    required this.perPage,
    required this.total,
    required this.lastPage,
  });

  factory Data104.fromJson(Map<String, Object?> json) =>
      _$Data104FromJson(json);

  @JsonKey(name: 'current_page')
  final int currentPage;
  final List<Data103> data;
  @JsonKey(name: 'per_page')
  final int perPage;
  final int total;
  @JsonKey(name: 'last_page')
  final int lastPage;

  Map<String, Object?> toJson() => _$Data104ToJson(this);
}
