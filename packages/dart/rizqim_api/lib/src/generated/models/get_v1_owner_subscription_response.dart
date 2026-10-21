// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data80.dart';

part 'get_v1_owner_subscription_response.g.dart';

@JsonSerializable()
class GetV1OwnerSubscriptionResponse {
  const GetV1OwnerSubscriptionResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerSubscriptionResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerSubscriptionResponseFromJson(json);

  final bool success;
  final String message;
  final Data80 data;

  Map<String, Object?> toJson() => _$GetV1OwnerSubscriptionResponseToJson(this);
}
