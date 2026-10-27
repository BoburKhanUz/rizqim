// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data41.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data41 _$Data41FromJson(Map<String, dynamic> json) => Data41(
  preferences: (json['preferences'] as List<dynamic>)
      .map((e) => Preferences2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data41ToJson(Data41 instance) => <String, dynamic>{
  'preferences': instance.preferences,
};
