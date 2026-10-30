// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data206.dart';

part 'post_v1_pos_clock_in_response.g.dart';

@JsonSerializable()
class PostV1PosClockInResponse {
  const PostV1PosClockInResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosClockInResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1PosClockInResponseFromJson(json);

  final bool success;
  final String message;
  final Data206 data;

  Map<String, Object?> toJson() => _$PostV1PosClockInResponseToJson(this);
}
