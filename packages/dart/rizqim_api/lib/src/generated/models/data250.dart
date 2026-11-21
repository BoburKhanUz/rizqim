// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data250.g.dart';

@JsonSerializable()
class Data250 {
  const Data250({required this.requestId, required this.claimed});

  factory Data250.fromJson(Map<String, Object?> json) =>
      _$Data250FromJson(json);

  @JsonKey(name: 'request_id')
  final String requestId;
  final bool claimed;

  Map<String, Object?> toJson() => _$Data250ToJson(this);
}
