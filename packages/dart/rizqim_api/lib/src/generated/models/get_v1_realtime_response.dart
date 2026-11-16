// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data360.dart';

part 'get_v1_realtime_response.g.dart';

@JsonSerializable()
class GetV1RealtimeResponse {
  const GetV1RealtimeResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1RealtimeResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1RealtimeResponseFromJson(json);

  final bool success;
  final String message;
  final Data360 data;

  Map<String, Object?> toJson() => _$GetV1RealtimeResponseToJson(this);
}
