// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'telegram.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Telegram _$TelegramFromJson(Map<String, dynamic> json) => Telegram(
  groupLink: json['group_link'] as String?,
  groupLinked: json['group_linked'] as bool,
);

Map<String, dynamic> _$TelegramToJson(Telegram instance) => <String, dynamic>{
  'group_link': instance.groupLink,
  'group_linked': instance.groupLinked,
};
