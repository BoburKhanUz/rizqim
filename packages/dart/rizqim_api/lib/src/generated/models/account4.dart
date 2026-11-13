// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'talon_mode.dart';

part 'account4.g.dart';

@JsonSerializable()
class Account4 {
  const Account4({
    required this.uuid,
    required this.name,
    required this.companyName,
    required this.inn,
    required this.contactName,
    required this.contactPhone,
    required this.invoiceEmail,
    required this.talonMode,
  });

  factory Account4.fromJson(Map<String, Object?> json) =>
      _$Account4FromJson(json);

  final String uuid;
  final String name;
  @JsonKey(name: 'company_name')
  final String? companyName;
  final String? inn;
  @JsonKey(name: 'contact_name')
  final String? contactName;
  @JsonKey(name: 'contact_phone')
  final String? contactPhone;
  @JsonKey(name: 'invoice_email')
  final String? invoiceEmail;
  @JsonKey(name: 'talon_mode')
  final TalonMode talonMode;

  Map<String, Object?> toJson() => _$Account4ToJson(this);
}
