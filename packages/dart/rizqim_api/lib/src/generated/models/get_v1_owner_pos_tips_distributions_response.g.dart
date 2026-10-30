// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_tips_distributions_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosTipsDistributionsResponse
_$GetV1OwnerPosTipsDistributionsResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerPosTipsDistributionsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data116.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerPosTipsDistributionsResponseToJson(
  GetV1OwnerPosTipsDistributionsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
