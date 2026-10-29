// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data204.dart';

part 'post_v1_pos_shifts_uuid_cash_movements_response.g.dart';

@JsonSerializable()
class PostV1PosShiftsUuidCashMovementsResponse {
  const PostV1PosShiftsUuidCashMovementsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosShiftsUuidCashMovementsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosShiftsUuidCashMovementsResponseFromJson(json);

  final bool success;
  final String message;
  final Data204 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosShiftsUuidCashMovementsResponseToJson(this);
}
