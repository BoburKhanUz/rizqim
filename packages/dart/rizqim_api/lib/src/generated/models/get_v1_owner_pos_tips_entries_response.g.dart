// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_tips_entries_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosTipsEntriesResponse _$GetV1OwnerPosTipsEntriesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPosTipsEntriesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data119.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerPosTipsEntriesResponseToJson(
  GetV1OwnerPosTipsEntriesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
