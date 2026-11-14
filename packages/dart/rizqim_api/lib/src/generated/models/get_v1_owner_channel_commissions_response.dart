// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data60.dart';

part 'get_v1_owner_channel_commissions_response.g.dart';

@JsonSerializable()
class GetV1OwnerChannelCommissionsResponse {
  const GetV1OwnerChannelCommissionsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerChannelCommissionsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerChannelCommissionsResponseFromJson(json);

  final bool success;
  final String message;
  final Data60 data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerChannelCommissionsResponseToJson(this);
}
