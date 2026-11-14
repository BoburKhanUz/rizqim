// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'rates3.dart';

part 'v1_owner_channel_commissions_request_body.g.dart';

@JsonSerializable()
class V1OwnerChannelCommissionsRequestBody {
  const V1OwnerChannelCommissionsRequestBody({required this.rates});

  factory V1OwnerChannelCommissionsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerChannelCommissionsRequestBodyFromJson(json);

  final List<Rates3> rates;

  Map<String, Object?> toJson() =>
      _$V1OwnerChannelCommissionsRequestBodyToJson(this);
}
