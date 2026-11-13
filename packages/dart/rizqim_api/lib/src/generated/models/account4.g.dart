// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'account4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Account4 _$Account4FromJson(Map<String, dynamic> json) => Account4(
  uuid: json['uuid'] as String,
  name: json['name'] as String,
  companyName: json['company_name'] as String?,
  inn: json['inn'] as String?,
  contactName: json['contact_name'] as String?,
  contactPhone: json['contact_phone'] as String?,
  invoiceEmail: json['invoice_email'] as String?,
  talonMode: TalonMode.fromJson(json['talon_mode'] as String),
);

Map<String, dynamic> _$Account4ToJson(Account4 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'name': instance.name,
  'company_name': instance.companyName,
  'inn': instance.inn,
  'contact_name': instance.contactName,
  'contact_phone': instance.contactPhone,
  'invoice_email': instance.invoiceEmail,
  'talon_mode': instance.talonMode,
};
