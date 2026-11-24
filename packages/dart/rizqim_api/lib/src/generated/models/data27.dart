// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data26.dart';

part 'data27.g.dart';

@JsonSerializable()
class Data27 {
  const Data27({
    required this.data,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  factory Data27.fromJson(Map<String, Object?> json) => _$Data27FromJson(json);

  final List<Data26> data;
  @JsonKey(name: 'current_page')
  final int currentPage;
  @JsonKey(name: 'last_page')
  final int lastPage;
  @JsonKey(name: 'per_page')
  final int perPage;
  final int total;

  Map<String, Object?> toJson() => _$Data27ToJson(this);
}
