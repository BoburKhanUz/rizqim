// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data46.dart';

part 'get_v1_owner_service_fees_response.g.dart';

@JsonSerializable()
class GetV1OwnerServiceFeesResponse {
  const GetV1OwnerServiceFeesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerServiceFeesResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerServiceFeesResponseFromJson(json);

  final bool success;
  final String message;
  final Data46 data;

  Map<String, Object?> toJson() => _$GetV1OwnerServiceFeesResponseToJson(this);
}
