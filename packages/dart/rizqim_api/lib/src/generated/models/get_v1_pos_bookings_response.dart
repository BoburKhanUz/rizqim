// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data141.dart';

part 'get_v1_pos_bookings_response.g.dart';

@JsonSerializable()
class GetV1PosBookingsResponse {
  const GetV1PosBookingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosBookingsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosBookingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data141 data;

  Map<String, Object?> toJson() => _$GetV1PosBookingsResponseToJson(this);
}
