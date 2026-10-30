// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data165.dart';

part 'get_v1_owner_waitlist_response.g.dart';

@JsonSerializable()
class GetV1OwnerWaitlistResponse {
  const GetV1OwnerWaitlistResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerWaitlistResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerWaitlistResponseFromJson(json);

  final bool success;
  final String message;
  final Data165 data;

  Map<String, Object?> toJson() => _$GetV1OwnerWaitlistResponseToJson(this);
}
