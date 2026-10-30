// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kitchen_station_kind.dart';

part 'v1_owner_kitchen_stations_request_body.g.dart';

@JsonSerializable()
class V1OwnerKitchenStationsRequestBody {
  const V1OwnerKitchenStationsRequestBody({
    required this.name,
    this.branchId,
    this.kind,
    this.targetSeconds,
    this.sortOrder,
    this.isActive,
    this.showOnKds,
  });

  factory V1OwnerKitchenStationsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerKitchenStationsRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  final String name;
  final KitchenStationKind? kind;
  @JsonKey(name: 'target_seconds')
  final int? targetSeconds;
  @JsonKey(name: 'sort_order')
  final int? sortOrder;
  @JsonKey(name: 'is_active')
  final bool? isActive;
  @JsonKey(name: 'show_on_kds')
  final bool? showOnKds;

  Map<String, Object?> toJson() =>
      _$V1OwnerKitchenStationsRequestBodyToJson(this);
}
