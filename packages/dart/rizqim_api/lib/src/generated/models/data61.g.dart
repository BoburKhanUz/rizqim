// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data61.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data61 _$Data61FromJson(Map<String, dynamic> json) => Data61(
  storeId: (json['store_id'] as num).toInt(),
  inviteLink: json['invite_link'] as String,
);

Map<String, dynamic> _$Data61ToJson(Data61 instance) => <String, dynamic>{
  'store_id': instance.storeId,
  'invite_link': instance.inviteLink,
};
