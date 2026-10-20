// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_stores_store_id_rate_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1StoresStoreIdRateRequestBody _$V1StoresStoreIdRateRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1StoresStoreIdRateRequestBody(
  rating: (json['rating'] as num).toInt(),
  comment: json['comment'] as String?,
  orderId: (json['order_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1StoresStoreIdRateRequestBodyToJson(
  V1StoresStoreIdRateRequestBody instance,
) => <String, dynamic>{
  'rating': instance.rating,
  'comment': instance.comment,
  'order_id': instance.orderId,
};
