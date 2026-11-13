// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch10.dart';
import 'sale2.dart';
import 'buyer2.dart';
import 'fiscal5.dart';
import 'blocked3.dart';
import 'print2.dart';

part 'voucher.g.dart';

@JsonSerializable()
class Voucher {
  const Voucher({
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

  factory Voucher.fromJson(Map<String, Object?> json) =>
      _$VoucherFromJson(json);

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
  final Branch10? branch;
  final Sale2 sale;
  final Buyer2 buyer;
  final String? note;
  final Fiscal5 fiscal;
  final Blocked3? blocked;
  final Print2? print;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$VoucherToJson(this);
}
