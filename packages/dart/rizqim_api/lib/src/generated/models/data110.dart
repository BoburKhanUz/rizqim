// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data109.dart';

part 'data110.g.dart';

@JsonSerializable()
class Data110 {
  const Data110({
    required this.currentPage,
    required this.data,
    required this.perPage,
    required this.total,
    required this.lastPage,
  });

  factory Data110.fromJson(Map<String, Object?> json) =>
      _$Data110FromJson(json);

  @JsonKey(name: 'current_page')
  final int currentPage;
  final List<Data109> data;
  @JsonKey(name: 'per_page')
  final int perPage;
  final int total;
  @JsonKey(name: 'last_page')
  final int lastPage;

  Map<String, Object?> toJson() => _$Data110ToJson(this);
}
