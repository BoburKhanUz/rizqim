// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data287.g.dart';

@JsonSerializable()
class Data287 {
  const Data287({required this.rating, required this.reviewCount});

  factory Data287.fromJson(Map<String, Object?> json) =>
      _$Data287FromJson(json);

  final String rating;
  @JsonKey(name: 'review_count')
  final int reviewCount;

  Map<String, Object?> toJson() => _$Data287ToJson(this);
}
