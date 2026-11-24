// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data21.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data21 _$Data21FromJson(Map<String, dynamic> json) => Data21(
  preferences: (json['preferences'] as List<dynamic>)
      .map((e) => Preferences2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data21ToJson(Data21 instance) => <String, dynamic>{
  'preferences': instance.preferences,
};
