// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data247.g.dart';

@JsonSerializable()
class Data247 {
  const Data247({required this.requestId, required this.claimed});

  factory Data247.fromJson(Map<String, Object?> json) =>
      _$Data247FromJson(json);

  @JsonKey(name: 'request_id')
  final String requestId;
  final bool claimed;

  Map<String, Object?> toJson() => _$Data247ToJson(this);
}
