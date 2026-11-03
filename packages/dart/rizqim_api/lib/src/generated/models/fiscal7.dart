// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'receipt4.dart';

part 'fiscal7.g.dart';

@JsonSerializable()
class Fiscal7 {
  const Fiscal7({
    required this.status,
    required this.receiptUuid,
    required this.error,
    required this.receipt,
  });

  factory Fiscal7.fromJson(Map<String, Object?> json) =>
      _$Fiscal7FromJson(json);

  final String? status;
  @JsonKey(name: 'receipt_uuid')
  final String? receiptUuid;
  final String? error;
  final Receipt4? receipt;

  Map<String, Object?> toJson() => _$Fiscal7ToJson(this);
}
