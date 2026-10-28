// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data110.dart';

part 'get_v1_owner_staff_response.g.dart';

@JsonSerializable()
class GetV1OwnerStaffResponse {
  const GetV1OwnerStaffResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerStaffResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerStaffResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data110> data;

  Map<String, Object?> toJson() => _$GetV1OwnerStaffResponseToJson(this);
}
