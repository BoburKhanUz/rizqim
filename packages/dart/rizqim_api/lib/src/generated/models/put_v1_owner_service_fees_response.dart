// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data48.dart';

part 'put_v1_owner_service_fees_response.g.dart';

@JsonSerializable()
class PutV1OwnerServiceFeesResponse {
  const PutV1OwnerServiceFeesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerServiceFeesResponse.fromJson(Map<String, Object?> json) =>
      _$PutV1OwnerServiceFeesResponseFromJson(json);

  final bool success;
  final String message;
  final Data48 data;

  Map<String, Object?> toJson() => _$PutV1OwnerServiceFeesResponseToJson(this);
}
