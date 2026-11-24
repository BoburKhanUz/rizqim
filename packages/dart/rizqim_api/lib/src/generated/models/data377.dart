// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data377.g.dart';

@JsonSerializable()
class Data377 {
  const Data377({required this.rating, required this.reviewCount});

  factory Data377.fromJson(Map<String, Object?> json) =>
      _$Data377FromJson(json);

  final String rating;
  @JsonKey(name: 'review_count')
  final int reviewCount;

  Map<String, Object?> toJson() => _$Data377ToJson(this);
}
