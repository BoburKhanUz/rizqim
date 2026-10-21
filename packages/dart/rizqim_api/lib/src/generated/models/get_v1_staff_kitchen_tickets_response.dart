// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data82.dart';
import 'meta.dart';

part 'get_v1_staff_kitchen_tickets_response.g.dart';

@JsonSerializable()
class GetV1StaffKitchenTicketsResponse {
  const GetV1StaffKitchenTicketsResponse({
    required this.success,
    required this.message,
    required this.data,
    required this.meta,
  });

  factory GetV1StaffKitchenTicketsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1StaffKitchenTicketsResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data82> data;
  final Meta meta;

  Map<String, Object?> toJson() =>
      _$GetV1StaffKitchenTicketsResponseToJson(this);
}
