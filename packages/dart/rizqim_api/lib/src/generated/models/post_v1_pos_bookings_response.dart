// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data187.dart';

part 'post_v1_pos_bookings_response.g.dart';

@JsonSerializable()
class PostV1PosBookingsResponse {
  const PostV1PosBookingsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBookingsResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1PosBookingsResponseFromJson(json);

  final bool success;
  final String message;
  final Data187 data;

  Map<String, Object?> toJson() => _$PostV1PosBookingsResponseToJson(this);
}
