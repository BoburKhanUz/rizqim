// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data46.dart';

part 'data47.g.dart';

@JsonSerializable()
class Data47 {
  const Data47({
    required this.data,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  factory Data47.fromJson(Map<String, Object?> json) => _$Data47FromJson(json);

  final List<Data46> data;
  @JsonKey(name: 'current_page')
  final int currentPage;
  @JsonKey(name: 'last_page')
  final int lastPage;
  @JsonKey(name: 'per_page')
  final int perPage;
  final int total;

  Map<String, Object?> toJson() => _$Data47ToJson(this);
}
