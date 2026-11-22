// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data161.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data161 _$Data161FromJson(Map<String, dynamic> json) => Data161(
  enforce: json['enforce'] as bool,
  signingTills: (json['signing_tills'] as num).toInt(),
  unsignedTills: (json['unsigned_tills'] as List<dynamic>)
      .map((e) => UnsignedTills.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data161ToJson(Data161 instance) => <String, dynamic>{
  'enforce': instance.enforce,
  'signing_tills': instance.signingTills,
  'unsigned_tills': instance.unsignedTills,
};
