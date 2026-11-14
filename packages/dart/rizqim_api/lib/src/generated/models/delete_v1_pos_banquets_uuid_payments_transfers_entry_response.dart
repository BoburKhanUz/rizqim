// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data234.dart';

part 'delete_v1_pos_banquets_uuid_payments_transfers_entry_response.g.dart';

@JsonSerializable()
class DeleteV1PosBanquetsUuidPaymentsTransfersEntryResponse {
  const DeleteV1PosBanquetsUuidPaymentsTransfersEntryResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1PosBanquetsUuidPaymentsTransfersEntryResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1PosBanquetsUuidPaymentsTransfersEntryResponseFromJson(json);

  final bool success;
  final String message;
  final Data234 data;

  Map<String, Object?> toJson() =>
      _$DeleteV1PosBanquetsUuidPaymentsTransfersEntryResponseToJson(this);
}
