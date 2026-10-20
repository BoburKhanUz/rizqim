// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data20.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data20 _$Data20FromJson(Map<String, dynamic> json) => Data20(
  preferences: (json['preferences'] as List<dynamic>)
      .map((e) => Preferences2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data20ToJson(Data20 instance) => <String, dynamic>{
  'preferences': instance.preferences,
};
