// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_kitchen_station_routes_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerKitchenStationRoutesRequestBody
_$V1OwnerKitchenStationRoutesRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerKitchenStationRoutesRequestBody(
      stationId: (json['station_id'] as num?)?.toInt(),
      branchId: (json['branch_id'] as num?)?.toInt(),
      categoryIds: (json['category_ids'] as List<dynamic>?)
          ?.map((e) => (e as num).toInt())
          .toList(),
      productIds: (json['product_ids'] as List<dynamic>?)
          ?.map((e) => (e as num).toInt())
          .toList(),
    );

Map<String, dynamic> _$V1OwnerKitchenStationRoutesRequestBodyToJson(
  V1OwnerKitchenStationRoutesRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'station_id': instance.stationId,
  'category_ids': instance.categoryIds,
  'product_ids': instance.productIds,
};
