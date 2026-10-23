// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'pending_receipts.g.dart';

@JsonSerializable()
class PendingReceipts {
  const PendingReceipts({required this.bills, required this.refunds});

  factory PendingReceipts.fromJson(Map<String, Object?> json) =>
      _$PendingReceiptsFromJson(json);

  final List<String> bills;
  final List<String> refunds;

  Map<String, Object?> toJson() => _$PendingReceiptsToJson(this);
}
