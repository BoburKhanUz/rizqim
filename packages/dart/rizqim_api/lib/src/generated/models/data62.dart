// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data61.dart';

part 'data62.g.dart';

@JsonSerializable()
class Data62 {
  const Data62({
    required this.data,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.from,
    required this.to,
  });

  factory Data62.fromJson(Map<String, Object?> json) => _$Data62FromJson(json);

  final List<Data61> data;
  @JsonKey(name: 'current_page')
  final int currentPage;
  @JsonKey(name: 'last_page')
  final int lastPage;
  @JsonKey(name: 'per_page')
  final int perPage;
  final int total;
  final int? from;
  final int? to;

  Map<String, Object?> toJson() => _$Data62ToJson(this);
}
