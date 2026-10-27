// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_subscription_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerSubscriptionResponse _$GetV1OwnerSubscriptionResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerSubscriptionResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data137.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerSubscriptionResponseToJson(
  GetV1OwnerSubscriptionResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
