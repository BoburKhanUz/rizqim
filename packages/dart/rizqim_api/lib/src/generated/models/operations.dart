// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'type9.dart';

part 'operations.g.dart';

@JsonSerializable()
class Operations {
  const Operations({
    this.opId,
    this.seq,
    this.type,
    this.occurredAt,
    this.staffId,
    this.payload,
    this.originDevice,
    this.signed,
    this.mac,
    this.prev,
    this.kid,
  });

  factory Operations.fromJson(Map<String, Object?> json) =>
      _$OperationsFromJson(json);

  /// The operation's own fields: required in the unsigned shape; in the signed shape they are read from `signed`
  @JsonKey(name: 'op_id')
  final String? opId;
  final int? seq;
  final Type9? type;
  @JsonKey(name: 'occurred_at')
  final DateTime? occurredAt;
  @JsonKey(name: 'staff_id')
  final int? staffId;
  final List<String>? payload;
  @JsonKey(name: 'origin_device')
  final String? originDevice;

  /// The signed shape (wave M5 S3): the whole operation (op_id, seq, type, occurred_at, staff_id, payload, origin_device) as JSON text, exactly the bytes the till wrote to its journal. Fields beside it are ignored.
  final String? signed;

  /// HMAC-SHA256(key, prev || signed), 64 hex characters.
  final String? mac;

  /// The mac of the operation before it (the first under a key: HMAC-SHA256(key, "rizqim-sync/v1/" + device uuid)), 64 hex characters.
  final String? prev;

  /// The number of the key it was signed with.
  final int? kid;

  Map<String, Object?> toJson() => _$OperationsToJson(this);
}
