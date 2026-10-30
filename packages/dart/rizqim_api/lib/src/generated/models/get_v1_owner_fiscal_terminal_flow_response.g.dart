// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_fiscal_terminal_flow_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerFiscalTerminalFlowResponse
_$GetV1OwnerFiscalTerminalFlowResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerFiscalTerminalFlowResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data85.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerFiscalTerminalFlowResponseToJson(
  GetV1OwnerFiscalTerminalFlowResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
