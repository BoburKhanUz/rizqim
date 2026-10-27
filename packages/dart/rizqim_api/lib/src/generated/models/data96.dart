// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data95.dart';

part 'data96.g.dart';

@JsonSerializable()
class Data96 {
  const Data96({
    required this.currentPage,
    required this.data,
    required this.perPage,
    required this.total,
    required this.lastPage,
  });

  factory Data96.fromJson(Map<String, Object?> json) => _$Data96FromJson(json);

  @JsonKey(name: 'current_page')
  final int currentPage;
  final List<Data95> data;
  @JsonKey(name: 'per_page')
  final int perPage;
  final int total;
  @JsonKey(name: 'last_page')
  final int lastPage;

  Map<String, Object?> toJson() => _$Data96ToJson(this);
}
