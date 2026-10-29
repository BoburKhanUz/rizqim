// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data199.dart';

part 'get_v1_pos_shifts_response.g.dart';

@JsonSerializable()
class GetV1PosShiftsResponse {
  const GetV1PosShiftsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosShiftsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosShiftsResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data199> data;

  Map<String, Object?> toJson() => _$GetV1PosShiftsResponseToJson(this);
}
