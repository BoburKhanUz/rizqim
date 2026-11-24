// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data164.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data164 _$Data164FromJson(Map<String, dynamic> json) => Data164(
  enforce: json['enforce'] as bool,
  signingTills: (json['signing_tills'] as num).toInt(),
  unsignedTills: (json['unsigned_tills'] as List<dynamic>)
      .map((e) => UnsignedTills2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data164ToJson(Data164 instance) => <String, dynamic>{
  'enforce': instance.enforce,
  'signing_tills': instance.signingTills,
  'unsigned_tills': instance.unsignedTills,
};
