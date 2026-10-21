// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_kitchen_stations_request_body.g.dart';

@JsonSerializable()
class V1OwnerKitchenStationsRequestBody {
  const V1OwnerKitchenStationsRequestBody({this.branchId});

  factory V1OwnerKitchenStationsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerKitchenStationsRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;

  Map<String, Object?> toJson() =>
      _$V1OwnerKitchenStationsRequestBodyToJson(this);
}
