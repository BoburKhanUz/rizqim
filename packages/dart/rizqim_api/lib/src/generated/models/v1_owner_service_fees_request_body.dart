// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'service_charge_channels.dart';
import 'place_fee_mode.dart';
import 'place_fee_channels.dart';

part 'v1_owner_service_fees_request_body.g.dart';

@JsonSerializable()
class V1OwnerServiceFeesRequestBody {
  const V1OwnerServiceFeesRequestBody({
    required this.serviceChargePercent,
    required this.placeFeeAmount,
    required this.placeFeeMode,
    this.branchId,
    this.serviceChargeChannels,
    this.placeFeeChannels,
  });

  factory V1OwnerServiceFeesRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerServiceFeesRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'service_charge_percent')
  final num serviceChargePercent;
  @JsonKey(name: 'service_charge_channels')
  final List<ServiceChargeChannels>? serviceChargeChannels;
  @JsonKey(name: 'place_fee_amount')
  final num placeFeeAmount;
  @JsonKey(name: 'place_fee_mode')
  final PlaceFeeMode placeFeeMode;
  @JsonKey(name: 'place_fee_channels')
  final List<PlaceFeeChannels>? placeFeeChannels;

  Map<String, Object?> toJson() => _$V1OwnerServiceFeesRequestBodyToJson(this);
}
