// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data189.dart';

part 'get_v1_pos_clock_response.g.dart';

@JsonSerializable()
class GetV1PosClockResponse {
  const GetV1PosClockResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosClockResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosClockResponseFromJson(json);

  final bool success;
  final String message;
  final Data189 data;

  Map<String, Object?> toJson() => _$GetV1PosClockResponseToJson(this);
}
