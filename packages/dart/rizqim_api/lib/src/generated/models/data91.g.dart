// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data91.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data91 _$Data91FromJson(Map<String, dynamic> json) => Data91(
  storeId: (json['store_id'] as num).toInt(),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data91ToJson(Data91 instance) => <String, dynamic>{
  'store_id': instance.storeId,
  'invite_link': instance.inviteLink,
};
