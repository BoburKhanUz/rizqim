// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_banquets_shopping_list_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosBanquetsShoppingListResponse
_$GetV1PosBanquetsShoppingListResponseFromJson(Map<String, dynamic> json) =>
    GetV1PosBanquetsShoppingListResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data223.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1PosBanquetsShoppingListResponseToJson(
  GetV1PosBanquetsShoppingListResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
