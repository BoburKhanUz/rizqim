// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch12.dart';
import 'sale4.dart';
import 'buyer4.dart';
import 'fiscal7.dart';
import 'blocked5.dart';
import 'print4.dart';

part 'data148.g.dart';

@JsonSerializable()
class Data148 {
  const Data148({
    required this.uuid,
    required this.code,
    required this.kind,
    required this.faceValue,
    required this.balance,
    required this.currency,
    required this.validFrom,
    required this.validUntil,
    required this.status,
    required this.statusLabel,
    required this.branch,
    required this.sale,
    required this.buyer,
    required this.note,
    required this.fiscal,
    required this.blocked,
    required this.print,
    required this.createdAt,
  });

  factory Data148.fromJson(Map<String, Object?> json) =>
      _$Data148FromJson(json);

  final String uuid;
  final String code;
  final String kind;
  @JsonKey(name: 'face_value')
  final String faceValue;
  final String balance;
  final String currency;
  @JsonKey(name: 'valid_from')
  final String validFrom;
  @JsonKey(name: 'valid_until')
  final String? validUntil;
  final String status;
  @JsonKey(name: 'status_label')
  final String statusLabel;
  final Branch12? branch;
  final Sale4 sale;
  final Buyer4 buyer;
  final String? note;
  final Fiscal7 fiscal;
  final Blocked5? blocked;
  final Print4 print;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Data148ToJson(this);
}
