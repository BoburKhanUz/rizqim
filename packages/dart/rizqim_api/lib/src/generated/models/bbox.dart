// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'bbox.g.dart';

@JsonSerializable()
class Bbox {
  const Bbox({
    required this.minLat,
    required this.maxLat,
    required this.minLng,
    required this.maxLng,
  });

  factory Bbox.fromJson(Map<String, Object?> json) => _$BboxFromJson(json);

  @JsonKey(name: 'min_lat')
  final String minLat;
  @JsonKey(name: 'max_lat')
  final String maxLat;
  @JsonKey(name: 'min_lng')
  final String minLng;
  @JsonKey(name: 'max_lng')
  final String maxLng;

  Map<String, Object?> toJson() => _$BboxToJson(this);
}
