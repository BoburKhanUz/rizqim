// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data251.dart';

part 'get_v1_pos_waitlist_response.g.dart';

@JsonSerializable()
class GetV1PosWaitlistResponse {
  const GetV1PosWaitlistResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosWaitlistResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosWaitlistResponseFromJson(json);

  final bool success;
  final String message;
  final Data251 data;

  Map<String, Object?> toJson() => _$GetV1PosWaitlistResponseToJson(this);
}
