// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data193.dart';

part 'get_v1_pos_queue_response.g.dart';

@JsonSerializable()
class GetV1PosQueueResponse {
  const GetV1PosQueueResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosQueueResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosQueueResponseFromJson(json);

  final bool success;
  final String message;
  final Data193 data;

  Map<String, Object?> toJson() => _$GetV1PosQueueResponseToJson(this);
}
