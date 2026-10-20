// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data57.g.dart';

@JsonSerializable()
class Data57 {
  const Data57({required this.rating, required this.reviewCount});

  factory Data57.fromJson(Map<String, Object?> json) => _$Data57FromJson(json);

  final String rating;
  @JsonKey(name: 'review_count')
  final int reviewCount;

  Map<String, Object?> toJson() => _$Data57ToJson(this);
}
