// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_kitchen_station_routes_request_body.g.dart';

@JsonSerializable()
class V1OwnerKitchenStationRoutesRequestBody {
  const V1OwnerKitchenStationRoutesRequestBody({this.branchId});

  factory V1OwnerKitchenStationRoutesRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerKitchenStationRoutesRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;

  Map<String, Object?> toJson() =>
      _$V1OwnerKitchenStationRoutesRequestBodyToJson(this);
}
